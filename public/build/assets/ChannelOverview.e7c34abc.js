import{_ as y}from"./Button.6e95bfad.js";import{_ as M}from"./Modal.01f30ff8.js";import{_ as j}from"./MultiSelect.7c5551be.js";import{g as f,K as $,h as E,c as x,a as p,w as u,q as F,o as s,b as e,f as n,l as d,t as a,d as O,u as c,k as N,F as P,n as h,O as S}from"./app.2e671246.js";import{r as I,a as L}from"./ChevronDoubleUpIcon.bf43cb6f.js";import{r as T}from"./PencilSquareIcon.66cbce5b.js";import{r as U}from"./CheckCircleIcon.84dcd428.js";import"./open-closed.94268395.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.8b62ab31.js";const Y={class:"flex flex-col md:flex-row space-x-2"},z={key:0,class:"text-gray-600"},A={key:1},J={key:2},K={class:"flex flex-col"},G={class:"-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5"},H={class:"inline-block min-w-full py-2 align-middle md:px-4 lg:px-6"},Q={class:"overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg"},R={class:"table-fixed min-w-full divide-y divide-gray-300"},W={class:"bg-gray-50"},X=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," # ",-1),Z={key:0,scope:"col",class:"w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},ee=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Sold ",-1),te=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Bal ",-1),se=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Cap ",-1),oe={scope:"col",class:"w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},ne={key:0},re=e("th",{scope:"col",class:"w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"}," Error ",-1),de={key:1,scope:"col",class:"w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},ae={key:2,scope:"col",class:"w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"},ce={class:"bg-white"},le={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center"},ie={key:0,class:"whitespace-nowrap text-sm font-semibold text-gray-900 text-center"},pe={class:"flex justify-center items-center"},ue=["src"],me={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-blue-600 sm:pl-6 text-center"},_e={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},xe={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},ye={class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},fe={class:"py-1 pl-1 pr-1 text-xs font-medium text-gray-900 sm:pl-1 text-center"},ge={key:0},ve={class:"font-bold"},he={key:1,class:"py-4 text-sm font-semibold text-gray-900 text-center"},be={key:0},we={key:0},ke={key:1,class:"break-normal text-xs"},Ce=e("br",null,null,-1),$e={key:1,class:"font-normal text-xs text-gray-700"},Oe={key:2,class:"whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"},Se={class:"flex justify-center space-x-1"},De=e("span",null," Dispense ",-1),Ve={class:"flex justify-between"},Be={key:0,class:"flex space-x-1"},qe=e("span",null," Edit Product ",-1),Me={key:1,class:"flex space-x-1"},je=e("span",null," Save ",-1),Ee={key:0,class:"flex flex-col text-blue-800 text-sm p-3"},Fe={key:0,class:""},Ne={key:1},Ke={__name:"ChannelOverview",props:{productOptions:Object,vend:Object,showModal:Boolean},emits:["modalClose"],setup(r,{emit:b}){const m=r,g=f([]),w=$().props.auth.permissions,k=f([]),_=f(!1);E(()=>{k.value=m.productOptions.data.map(o=>({id:o.id,full_name:o.full_name+(o.desc?" "+o.desc:"")})),g.value=m.vend.vendChannelsJson.map(o=>({...o,product:o.product?{...o.product,option_data:{id:o.product.id,full_name:o.product.code+" - "+o.product.name+(o.product.desc?" "+o.product.desc:"")}}:null}))});const v=$().props.auth.profile,i=f(!1);function D(o){S.post("/vends/"+m.vend.id+"/dispense-product",{channel_id:o.id},{preserveScroll:!0,onSuccess:()=>{b("modalClose")}})}function V(){i.value?S.post("/vends/"+m.vend.id+"/edit-products",{channels:g.value.map(o=>({id:o.id,product_id:o.product.id,edited_product_id:o.product.option_data.id}))},{preserveScroll:!0,onSuccess:()=>{i.value=!1,b("modalClose")}}):i.value=!0}function B(o){return moment(o).format("YYMMDD hh:mm A")}return(o,l)=>(s(),x(F,{to:"body"},[p(M,{open:r.showModal,onModalClose:l[3]||(l[3]=t=>o.$emit("modalClose"))},{header:u(()=>[e("div",Y,[m.profile?(s(),n("span",z," Channel Overview ")):d("",!0),r.vend.code?(s(),n("span",A," ID# "+a(r.vend.code),1)):d("",!0),r.vend.customer_code?(s(),n("span",J," ("+a(r.vend.customer_code?r.vend.customer_code:null)+") "+a(r.vend.customer_name?r.vend.customer_name:null),1)):d("",!0)])]),default:u(()=>[e("div",K,[e("div",G,[e("div",H,[e("div",Q,[e("table",R,[e("thead",W,[e("tr",null,[X,r.vend.product_mapping_name?(s(),n("th",Z," Image ")):d("",!0),ee,te,se,e("th",oe,[O(" Price "),c(v)&&c(v).base_currency?(s(),n("span",ne," ("+a(c(v).base_currency.currency_symbol)+") ",1)):d("",!0)]),re,r.vend.product_mapping_name?(s(),n("th",de," Product ")):d("",!0),c(w).includes("admin-access vends")?(s(),n("th",ae,[!_.value&&r.vend.is_mqtt?(s(),x(y,{key:0,class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-200 px-4 py-2 md:px-3 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[0]||(l[0]=t=>_.value=!0)},{default:u(()=>[p(c(I),{class:"h-3 w-3","aria-hidden":"true"})]),_:1})):d("",!0),_.value&&r.vend.is_mqtt?(s(),x(y,{key:1,class:"inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-4 py-2 md:px-3 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2",onClick:l[1]||(l[1]=t=>_.value=!1)},{default:u(()=>[p(c(L),{class:"h-3 w-3","aria-hidden":"true"})]),_:1})):d("",!0)])):d("",!0)])]),e("tbody",ce,[(s(!0),n(P,null,N(g.value,(t,q)=>(s(),n("tr",{key:t.id,class:h(q%2===0?void 0:"bg-gray-50")},[e("td",le,a(t.code),1),r.vend.product_mapping_name?(s(),n("td",ie,[e("div",pe,[t.product&&t.product.thumbnail?(s(),n("img",{key:0,class:"h-16 w-16 rounded-full",src:t.product.thumbnail.full_url,alt:""},null,8,ue)):d("",!0)])])):d("",!0),e("td",me,a(t.capacity-t.qty),1),e("td",_e,a(t.qty),1),e("td",xe,a(t.capacity),1),e("td",ye,a((t.amount/100).toLocaleString(void 0,{minimumFractionDigits:2})),1),e("td",fe,[t.vend_channel_error_logs&&t.vend_channel_error_logs[0]&&t.vend_channel_error_logs[0].is_error_cleared==0?(s(),n("span",ge,[e("div",{class:h([t.vend_channel_error_logs[0].vend_channel_error.code==4||t.vend_channel_error_logs[0].vend_channel_error.code==5||t.vend_channel_error_logs[0].vend_channel_error.code==7?" text-blue-800":" text-red-800"])},[e("span",ve," ("+a(t.vend_channel_error_logs[0].vend_channel_error.code)+") ",1),e("div",null,a(B(t.vend_channel_error_logs[0].created_at)),1)],2)])):d("",!0)]),r.vend.product_mapping_name?(s(),n("td",he,[i.value?(s(),n("span",$e,[p(j,{modelValue:t.product.option_data,"onUpdate:modelValue":C=>t.product.option_data=C,options:k.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","onUpdate:modelValue","options"])])):(s(),n("span",be,[t.product&&t.product.code?(s(),n("span",we,a(t.product.code),1)):d("",!0),t.product&&t.product.name?(s(),n("span",ke,[Ce,O(" "+a(t.product.name),1)])):d("",!0)]))])):d("",!0),c(w).includes("admin-access vends")?(s(),n("td",Oe,[e("div",Se,[r.vend.is_mqtt&&_.value?(s(),x(y,{key:0,type:"button",class:"bg-yellow-300 hover:bg-yellow-400 px-1 py-1 text-xs text-gray-800 flex space-x-1",onClick:C=>D(t)},{default:u(()=>[De]),_:2},1032,["onClick"])):d("",!0)])])):d("",!0)],2))),128))])])])])])]),e("div",Ve,[e("span",null,[r.vend.product_mapping_name?(s(),x(y,{key:0,type:"button",class:h(["px-2 py-1 mt-2 ml-1 text-xs flex space-x-1",[i.value?"bg-green-300 hover:bg-green-400 text-green-800":"bg-gray-300 hover:bg-gray-400 text-gray-800"]]),onClick:l[2]||(l[2]=t=>V())},{default:u(()=>[i.value?(s(),n("span",Me,[p(c(U),{class:"w-4 h-4"}),je])):(s(),n("span",Be,[p(c(T),{class:"w-4 h-4"}),qe]))]),_:1},8,["class"])):d("",!0)]),r.vend.product_mapping_name?(s(),n("span",Ee,[r.vend.product_mapping_name?(s(),n("span",Fe,a(r.vend.product_mapping_name),1)):d("",!0),r.vend.product_mapping_remarks?(s(),n("span",Ne,a(r.vend.product_mapping_remarks),1)):d("",!0)])):d("",!0)])]),_:1},8,["open"])]))}};export{Ke as default};