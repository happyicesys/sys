import{_ as h}from"./Button.309db67b.js";import{_ as M}from"./Modal.d13ca3c2.js";import{_ as P}from"./MultiSelect.48d24cef.js";import{g as b,K as w,h as j,c as v,a as x,w as y,p as E,o as t,b as s,f as o,l as n,t as c,d as k,u as a,k as L,F as N,n as C,O}from"./app.b81306b2.js";import{r as I,a as T}from"./ChevronDoubleUpIcon.3a2278c8.js";import{r as U}from"./PencilSquareIcon.a894001f.js";import{r as Y}from"./CheckCircleIcon.1823c523.js";import"./open-closed.e5e51ca3.js";import"./disposables.c5907830.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.921d596d.js";const z={class:"flex flex-col md:flex-row space-x-2"},A={key:0,class:"text-gray-600"},G={key:1},J={key:2},K={class:"flex flex-col"},Q={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},H={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},R={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},W={class:"table-fixed min-w-full divide-y divide-gray-300"},X={class:"bg-gray-50"},Z=s("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," # ",-1),ee={key:0,scope:"col",class:"w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},te=s("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Sold ",-1),se=s("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Bal ",-1),oe=s("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Cap ",-1),ne={scope:"col",class:"w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},re={key:0},de={key:1,scope:"col",class:"w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},ce={key:0},ae=s("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Group ",-1),ie=s("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Error ",-1),le={key:2,scope:"col",class:"w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},pe={key:3,scope:"col",class:"w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},ue={key:4,scope:"col",class:"w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},me={key:5,scope:"col",class:"w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},_e={class:"bg-white"},xe={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},ye={key:0,class:"whitespace-nowrap text-sm font-semibold text-gray-900 text-center"},fe={class:"flex justify-center items-center"},ge=["src"],ve={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-blue-600 sm:pl-6 text-center"},he={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},be={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},we={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},ke={key:1,class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Ce={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},$e={class:"py-1 pl-1 pr-1 text-xs font-medium text-gray-900 sm:pl-1 text-center"},De={key:0},Se={class:"font-bold"},Oe={key:2,class:"py-4 text-sm font-semibold text-gray-900 text-center"},qe={key:0},Ve={key:0},Be={key:1,class:"break-normal text-xs"},Fe=s("br",null,null,-1),Me={key:1,class:"font-normal text-xs text-gray-700"},Pe={key:3,class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},je={key:4,class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Ee={key:5,class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Le={class:"flex justify-center space-x-1"},Ne=s("span",null," Dispense ",-1),Ie={class:"flex justify-between"},Te={key:0,class:"flex space-x-1"},Ue=s("span",null," Edit Product ",-1),Ye={key:1,class:"flex space-x-1"},ze=s("span",null," Save ",-1),Ae={key:0,class:"flex flex-col text-blue-800 text-sm p-3"},Ge={key:0,class:""},Je={key:1},ot={__name:"ChannelOverview",props:{productOptions:Object,vend:Object,showModal:Boolean},emits:["modalClose"],setup(d,{emit:$}){const f=d,i=b([]),l=w().props.auth.operatorCountry,D=w().props.auth.permissions,S=b([]),g=b(!1);j(()=>{S.value=f.productOptions.data.map(r=>({id:r.id,full_name:r.full_name+(r.desc?" "+r.desc:"")})),i.value=f.vend.vendChannelsJson.map(r=>({...r,product:r.product?{...r.product,option_data:{id:r.product.id,full_name:r.product.code+" - "+r.product.name+(r.product.desc?" "+r.product.desc:"")}}:null}))});const m=w().props.auth.profile,_=b(!1);function q(r){O.post("/vends/"+f.vend.id+"/dispense-product",{channel_id:r.id},{preserveScroll:!0,onSuccess:()=>{$("modalClose")}})}function V(){_.value?O.post("/vends/"+f.vend.id+"/edit-products",{channels:i.value.map(r=>({id:r.id,product_id:r.product.id,edited_product_id:r.product.option_data.id}))},{preserveScroll:!0,onSuccess:()=>{_.value=!1,$("modalClose")}}):_.value=!0}function B(r){return moment(r).format("YYMMDD hh:mm A")}return(r,p)=>(t(),v(E,{to:"body"},[x(M,{open:d.showModal,onModalClose:p[3]||(p[3]=e=>r.$emit("modalClose"))},{header:y(()=>[s("div",z,[f.profile?(t(),o("span",A," Channel Overview ")):n("",!0),d.vend.code?(t(),o("span",G," ID# "+c(d.vend.code),1)):n("",!0),d.vend.customer_code?(t(),o("span",J," ("+c(d.vend.customer_code?d.vend.customer_code:null)+") "+c(d.vend.customer_name?d.vend.customer_name:null),1)):n("",!0)])]),default:y(()=>[s("div",K,[s("div",Q,[s("div",H,[s("div",R,[s("table",W,[s("thead",X,[s("tr",null,[Z,d.vend.product_mapping_name?(t(),o("th",ee," Image ")):n("",!0),te,se,oe,s("th",ne,[k(" Price "),a(m)&&a(m).base_currency?(t(),o("span",re," ("+c(a(m).base_currency.currency_symbol)+") ",1)):n("",!0)]),i.value.some(e=>"amount2"in e)?(t(),o("th",de,[k(" Price 2 "),a(m)&&a(m).base_currency?(t(),o("span",ce," ("+c(a(m).base_currency.currency_symbol)+") ",1)):n("",!0)])):n("",!0),ae,ie,d.vend.product_mapping_name?(t(),o("th",le," Product ")):n("",!0),i.value.some(e=>"sku_code"in e)?(t(),o("th",pe," Product ")):n("",!0),i.value.some(e=>"locked_qty"in e)?(t(),o("th",ue," Locked Qty ")):n("",!0),a(D).includes("admin-access vends")?(t(),o("th",me,[!g.value&&d.vend.is_mqtt?(t(),v(h,{key:0,class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-200 px-4 py-2 md:px-3 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:p[0]||(p[0]=e=>g.value=!0)},{default:y(()=>[x(a(I),{class:"h-3 w-3","aria-hidden":"true"})]),_:1})):n("",!0),g.value&&d.vend.is_mqtt?(t(),v(h,{key:1,class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-4 py-2 md:px-3 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:p[1]||(p[1]=e=>g.value=!1)},{default:y(()=>[x(a(T),{class:"h-3 w-3","aria-hidden":"true"})]),_:1})):n("",!0)])):n("",!0)])]),s("tbody",_e,[(t(!0),o(N,null,L(i.value,(e,F)=>(t(),o("tr",{key:e.id,class:C(F%2===0?void 0:"bg-gray-50")},[s("td",xe,c(e.code),1),d.vend.product_mapping_name?(t(),o("td",ye,[s("div",fe,[e.product&&e.product.thumbnail?(t(),o("img",{key:0,class:"h-16 w-16 rounded-full",src:e.product.thumbnail.full_url,alt:""},null,8,ge)):n("",!0)])])):n("",!0),s("td",ve,c(e.capacity-e.qty),1),s("td",he,c(e.qty),1),s("td",be,c(e.capacity),1),s("td",we,c((e.amount/100).toLocaleString(void 0,{minimumFractionDigits:a(l).is_currency_exponent_hidden?0:a(l).currency_exponent,maximumFractionDigits:a(l).is_currency_exponent_hidden?0:a(l).currency_exponent})),1),i.value.some(u=>"amount2"in u)?(t(),o("td",ke,c((e.amount2/100).toLocaleString(void 0,{minimumFractionDigits:a(l).is_currency_exponent_hidden?0:a(l).currency_exponent,maximumFractionDigits:a(l).is_currency_exponent_hidden?0:a(l).currency_exponent})),1)):n("",!0),s("td",Ce,c(e.discount_group),1),s("td",$e,[e.vend_channel_error_logs&&e.vend_channel_error_logs[0]&&e.vend_channel_error_logs[0].is_error_cleared==0?(t(),o("span",De,[s("div",{class:C([e.vend_channel_error_logs[0].vend_channel_error.code==4||e.vend_channel_error_logs[0].vend_channel_error.code==5||e.vend_channel_error_logs[0].vend_channel_error.code==7?" text-blue-800":" text-red-800"])},[s("span",Se," ("+c(e.vend_channel_error_logs[0].vend_channel_error.code)+") ",1),s("div",null,c(B(e.vend_channel_error_logs[0].created_at)),1)],2)])):n("",!0)]),d.vend.product_mapping_name?(t(),o("td",Oe,[_.value?(t(),o("span",Me,[x(P,{modelValue:e.product.option_data,"onUpdate:modelValue":u=>e.product.option_data=u,options:S.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","onUpdate:modelValue","options"])])):(t(),o("span",qe,[e.product&&e.product.code?(t(),o("span",Ve,c(e.product.code),1)):n("",!0),e.product&&e.product.name?(t(),o("span",Be,[Fe,k(" "+c(e.product.name),1)])):n("",!0)]))])):n("",!0),i.value.some(u=>"sku_code"in u)?(t(),o("td",Pe,c(e.sku_code),1)):n("",!0),i.value.some(u=>"locked_qty"in u)?(t(),o("td",je,c(e.locked_qty),1)):n("",!0),a(D).includes("admin-access vends")?(t(),o("td",Ee,[s("div",Le,[d.vend.is_mqtt&&g.value?(t(),v(h,{key:0,type:"button",class:"bg-yellow-300 hover:bg-yellow-400 px-1 py-1 text-xs text-gray-800 flex space-x-1",onClick:u=>q(e)},{default:y(()=>[Ne]),_:2},1032,["onClick"])):n("",!0)])])):n("",!0)],2))),128))])])])])])]),s("div",Ie,[s("span",null,[d.vend.product_mapping_name?(t(),v(h,{key:0,type:"button",class:C(["px-2 py-1 mt-2 ml-1 text-xs flex space-x-1",[_.value?"bg-green-300 hover:bg-green-400 text-green-800":"bg-gray-300 hover:bg-gray-400 text-gray-800"]]),onClick:p[2]||(p[2]=e=>V())},{default:y(()=>[_.value?(t(),o("span",Ye,[x(a(Y),{class:"w-4 h-4"}),ze])):(t(),o("span",Te,[x(a(U),{class:"w-4 h-4"}),Ue]))]),_:1},8,["class"])):n("",!0)]),d.vend.product_mapping_name?(t(),o("span",Ae,[d.vend.product_mapping_name?(t(),o("span",Ge,c(d.vend.product_mapping_name),1)):n("",!0),d.vend.product_mapping_remarks?(t(),o("span",Je,c(d.vend.product_mapping_remarks),1)):n("",!0)])):n("",!0)])]),_:1},8,["open"])]))}};export{ot as default};
