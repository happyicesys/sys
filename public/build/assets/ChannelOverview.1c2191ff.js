import{_ as h}from"./Button.4631b684.js";import{_ as P}from"./Modal.9639af57.js";import{_ as j}from"./MultiSelect.0c44488a.js";import{g as b,Q as w,h as E,c as v,a as x,w as y,p as L,o as t,b as s,f as o,l as n,t as c,d as k,u as a,k as N,F as I,n as C,O as S}from"./app.c4e47028.js";import{r as T,a as U}from"./ChevronDoubleUpIcon.6bf0efea.js";import{r as Y}from"./PencilSquareIcon.ac8e2982.js";import{r as z}from"./CheckCircleIcon.3fae9752.js";import"./keyboard.58689cfa.js";import"./disposables.be045d92.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.dac4e319.js";const A={class:"flex flex-col md:flex-row space-x-2"},G={key:0,class:"text-gray-600"},J={key:1},Q={key:2},H={class:"flex flex-col"},K={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},R={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},W={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},X={class:"table-fixed min-w-full divide-y divide-gray-300"},Z={class:"bg-gray-50"},ee=s("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," # ",-1),te={key:0,scope:"col",class:"w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},se=s("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Sold ",-1),oe=s("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Bal ",-1),ne=s("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Cap ",-1),re={scope:"col",class:"w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},de={key:0},ce={key:1,scope:"col",class:"w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},ae={key:0},ie=s("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Group ",-1),le=s("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Error ",-1),pe={key:2,scope:"col",class:"w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},ue={key:3,scope:"col",class:"w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},me={key:4,scope:"col",class:"w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},_e={key:5,scope:"col",class:"w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},xe={class:"bg-white"},ye={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},fe={key:0,class:"whitespace-nowrap text-sm font-semibold text-gray-900 text-center"},ge={class:"flex justify-center items-center"},ve=["src"],he={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-blue-600 sm:pl-6 text-center"},be={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},we={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},ke={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Ce={key:1,class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},De={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},$e={class:"py-1 pl-1 pr-1 text-xs font-medium text-gray-900 sm:pl-1 text-center"},Oe={key:0},Se={class:"font-bold"},qe={key:2,class:"py-4 text-sm font-semibold text-gray-900 text-center"},Ve={key:0},Be={key:0},Fe={key:1,class:"break-normal text-xs"},Me=s("br",null,null,-1),Pe={key:1,class:"font-normal text-xs text-gray-700"},je={key:3,class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Ee={key:4,class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Le={key:5,class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Ne={class:"flex justify-center space-x-1"},Ie=s("span",null," Dispense ",-1),Te={class:"flex justify-between"},Ue={key:0,class:"flex space-x-1"},Ye=s("span",null," Edit Product ",-1),ze={key:1,class:"flex space-x-1"},Ae=s("span",null," Save ",-1),Ge={key:0,class:"flex flex-col text-blue-800 text-sm p-3"},Je={key:0,class:""},Qe={key:1},nt={__name:"ChannelOverview",props:{productOptions:Object,vend:Object,showModal:Boolean},emits:["modalClose"],setup(d,{emit:q}){const f=d,i=b([]),l=w().props.auth.operatorCountry,D=w().props.auth.permissions,$=b([]),g=b(!1);E(()=>{$.value=f.productOptions.data.map(r=>({id:r.id,full_name:r.full_name+(r.desc?" "+r.desc:"")})),i.value=f.vend.vendChannelsJson.map(r=>({...r,product:r.product?{...r.product,option_data:{id:r.product.id,full_name:r.product.code+" - "+r.product.name+(r.product.desc?" "+r.product.desc:"")}}:null}))});const m=w().props.auth.profile,_=b(!1),O=q;function V(r){S.post("/vends/"+f.vend.id+"/dispense-product",{channel_id:r.id},{preserveScroll:!0,onSuccess:()=>{O("modalClose")}})}function B(){_.value?S.post("/vends/"+f.vend.id+"/edit-products",{channels:i.value.map(r=>({id:r.id,product_id:r.product.id,edited_product_id:r.product.option_data.id}))},{preserveScroll:!0,onSuccess:()=>{_.value=!1,O("modalClose")}}):_.value=!0}function F(r){return moment(r).format("YYMMDD hh:mm A")}return(r,p)=>(t(),v(L,{to:"body"},[x(P,{open:d.showModal,onModalClose:p[3]||(p[3]=e=>r.$emit("modalClose"))},{header:y(()=>[s("div",A,[f.profile?(t(),o("span",G," Channel Overview ")):n("",!0),d.vend.code?(t(),o("span",J," ID# "+c(d.vend.code),1)):n("",!0),d.vend.customer_code?(t(),o("span",Q," ("+c(d.vend.customer_code?d.vend.customer_code:null)+") "+c(d.vend.customer_name?d.vend.customer_name:null),1)):n("",!0)])]),default:y(()=>[s("div",H,[s("div",K,[s("div",R,[s("div",W,[s("table",X,[s("thead",Z,[s("tr",null,[ee,d.vend.product_mapping_name?(t(),o("th",te," Image ")):n("",!0),se,oe,ne,s("th",re,[k(" Price "),a(m)&&a(m).base_currency?(t(),o("span",de," ("+c(a(m).base_currency.currency_symbol)+") ",1)):n("",!0)]),i.value.some(e=>"amount2"in e)?(t(),o("th",ce,[k(" Price 2 "),a(m)&&a(m).base_currency?(t(),o("span",ae," ("+c(a(m).base_currency.currency_symbol)+") ",1)):n("",!0)])):n("",!0),ie,le,d.vend.product_mapping_name?(t(),o("th",pe," Product ")):n("",!0),i.value.some(e=>"sku_code"in e)?(t(),o("th",ue," Product Code ")):n("",!0),i.value.some(e=>"qty_not_available_duration"in e)?(t(),o("th",me," Last Out Of Stock Duration ")):n("",!0),a(D).includes("admin-access vends")?(t(),o("th",_e,[!g.value&&d.vend.is_mqtt?(t(),v(h,{key:0,class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-200 px-4 py-2 md:px-3 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:p[0]||(p[0]=e=>g.value=!0)},{default:y(()=>[x(a(T),{class:"h-3 w-3","aria-hidden":"true"})]),_:1})):n("",!0),g.value&&d.vend.is_mqtt?(t(),v(h,{key:1,class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-4 py-2 md:px-3 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:p[1]||(p[1]=e=>g.value=!1)},{default:y(()=>[x(a(U),{class:"h-3 w-3","aria-hidden":"true"})]),_:1})):n("",!0)])):n("",!0)])]),s("tbody",xe,[(t(!0),o(I,null,N(i.value,(e,M)=>(t(),o("tr",{key:e.id,class:C(M%2===0?void 0:"bg-gray-50")},[s("td",ye,c(e.code),1),d.vend.product_mapping_name?(t(),o("td",fe,[s("div",ge,[e.product&&e.product.thumbnail?(t(),o("img",{key:0,class:"h-16 w-16 rounded-full",src:e.product.thumbnail.full_url,alt:""},null,8,ve)):n("",!0)])])):n("",!0),s("td",he,c(e.capacity-e.qty),1),s("td",be,c(e.qty),1),s("td",we,c(e.capacity),1),s("td",ke,c((e.amount/100).toLocaleString(void 0,{minimumFractionDigits:a(l).is_currency_exponent_hidden?0:a(l).currency_exponent,maximumFractionDigits:a(l).is_currency_exponent_hidden?0:a(l).currency_exponent})),1),i.value.some(u=>"amount2"in u)?(t(),o("td",Ce,c((e.amount2/100).toLocaleString(void 0,{minimumFractionDigits:a(l).is_currency_exponent_hidden?0:a(l).currency_exponent,maximumFractionDigits:a(l).is_currency_exponent_hidden?0:a(l).currency_exponent})),1)):n("",!0),s("td",De,c(e.discount_group),1),s("td",$e,[e.vend_channel_error_logs&&e.vend_channel_error_logs[0]&&e.vend_channel_error_logs[0].is_error_cleared==0?(t(),o("span",Oe,[s("div",{class:C([e.vend_channel_error_logs[0].vend_channel_error.code==4||e.vend_channel_error_logs[0].vend_channel_error.code==5||e.vend_channel_error_logs[0].vend_channel_error.code==7?" text-blue-800":" text-red-800"])},[s("span",Se," ("+c(e.vend_channel_error_logs[0].vend_channel_error.code)+") ",1),s("div",null,c(F(e.vend_channel_error_logs[0].created_at)),1)],2)])):n("",!0)]),d.vend.product_mapping_name?(t(),o("td",qe,[_.value?(t(),o("span",Pe,[x(j,{modelValue:e.product.option_data,"onUpdate:modelValue":u=>e.product.option_data=u,options:$.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","onUpdate:modelValue","options"])])):(t(),o("span",Ve,[e.product&&e.product.code?(t(),o("span",Be,c(e.product.code),1)):n("",!0),e.product&&e.product.name?(t(),o("span",Fe,[Me,k(" "+c(e.product.name),1)])):n("",!0)]))])):n("",!0),i.value.some(u=>"sku_code"in u)?(t(),o("td",je,c(e.sku_code),1)):n("",!0),i.value.some(u=>"qty_not_available_duration"in u)?(t(),o("td",Ee,c(e.qty_not_available_duration),1)):n("",!0),a(D).includes("admin-access vends")?(t(),o("td",Le,[s("div",Ne,[d.vend.is_mqtt&&g.value?(t(),v(h,{key:0,type:"button",class:"bg-yellow-300 hover:bg-yellow-400 px-1 py-1 text-xs text-gray-800 flex space-x-1",onClick:u=>V(e)},{default:y(()=>[Ie]),_:2},1032,["onClick"])):n("",!0)])])):n("",!0)],2))),128))])])])])])]),s("div",Te,[s("span",null,[d.vend.product_mapping_name?(t(),v(h,{key:0,type:"button",class:C(["px-2 py-1 mt-2 ml-1 text-xs flex space-x-1",[_.value?"bg-green-300 hover:bg-green-400 text-green-800":"bg-gray-300 hover:bg-gray-400 text-gray-800"]]),onClick:p[2]||(p[2]=e=>B())},{default:y(()=>[_.value?(t(),o("span",ze,[x(a(z),{class:"w-4 h-4"}),Ae])):(t(),o("span",Ue,[x(a(Y),{class:"w-4 h-4"}),Ye]))]),_:1},8,["class"])):n("",!0)]),d.vend.product_mapping_name?(t(),o("span",Ge,[d.vend.product_mapping_name?(t(),o("span",Je,c(d.vend.product_mapping_name),1)):n("",!0),d.vend.product_mapping_remarks?(t(),o("span",Qe,c(d.vend.product_mapping_remarks),1)):n("",!0)])):n("",!0)])]),_:1},8,["open"])]))}};export{nt as default};
