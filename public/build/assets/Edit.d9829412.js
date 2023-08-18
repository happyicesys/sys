import{o as r,f as v,b as t,g,c as h,w as u,a as i,u as d,r as j,l as m,F as O,k as z,t as f,T as V,K as C,U as M,h as A,Z as I,d as p,n as $,e as q,m as E,v as F,i as H}from"./app.a6e7d30a.js";import{_ as K}from"./Authenticated.1dad9921.js";import{_ as S}from"./Button.d7b9178f.js";import{_ as Y}from"./DatePicker.449d4ec5.js";import{_ as B}from"./FormInput.cd94c3c3.js";import{_ as N}from"./MultiSelect.c7161ea4.js";import{H as P,$ as L,K as T,r as R,U as Z,_ as G,N as J}from"./MagnifyingGlassCircleIcon.a935c102.js";import{r as Q}from"./ArrowUturnLeftIcon.ca9f9fd3.js";import{r as W}from"./CheckCircleIcon.5f2ed21a.js";import"./open-closed.0f1dc1e0.js";import"./use-resolve-button-type.e0c9888a.js";import"./RectangleStackIcon.69bfd2ff.js";import"./main.77a7d7ab.js";import"./MultiSelect.vue_vue_type_style_index_1_lang.40130a9b.js";function X(s,a){return r(),v("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[t("path",{"fill-rule":"evenodd",d:"M2 10a8 8 0 1116 0 8 8 0 01-16 0zm5-2.25A.75.75 0 017.75 7h.5a.75.75 0 01.75.75v4.5a.75.75 0 01-.75.75h-.5a.75.75 0 01-.75-.75v-4.5zm4 0a.75.75 0 01.75-.75h.5a.75.75 0 01.75.75v4.5a.75.75 0 01-.75.75h-.5a.75.75 0 01-.75-.75v-4.5z","clip-rule":"evenodd"})])}function ee(s,a){return r(),v("svg",{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 20 20",fill:"currentColor","aria-hidden":"true"},[t("path",{d:"M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"})])}const te={class:"flex space-x-1"},se={key:0,class:"text-red-500"},oe={class:"relative mt-1"},ae=["onClick"],re={class:"block truncate"},le={key:1,class:"text-sm text-red-600"},ne={__name:"SearchVendCodeInput",props:{modelValue:[String,Number],required:[Boolean,String],error:String},emits:["update:modelValue","selected"],setup(s,{emit:a}){const x=g([]),e=_.debounce(async c=>{if(!c.target.value.length){x.value=[];return}axios({method:"get",url:"/api/vends/search/"+c.target.value}).then(y=>{x.value=y.data}).catch(y=>{console.log(y)})},300);function k(c){a("update:modelValue",c.target.value),e(c)}function b(c){a("selected",c)}return(c,y)=>(r(),h(d(J),{as:"div"},{default:u(()=>[t("div",te,[i(d(P),{class:"block text-sm font-medium text-gray-700"},{default:u(()=>[j(c.$slots,"default")]),_:3}),s.required?(r(),v("span",se," * ")):m("",!0)]),t("div",oe,[i(d(L),{class:"w-full rounded-md border border-gray-300 bg-white py-2 pl-3 pr-10 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm",onInput:k,value:s.modelValue},null,8,["value"]),i(d(T),{class:"absolute inset-y-0 right-0 flex items-center rounded-r-md px-2 focus:outline-none"},{default:u(()=>[i(d(R),{class:"h-5 w-5 text-gray-400","aria-hidden":"true"})]),_:1}),x.value.length>0?(r(),h(d(Z),{key:0,class:"absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm"},{default:u(()=>[(r(!0),v(O,null,z(x.value,w=>(r(),h(d(G),{as:"template"},{default:u(()=>[t("li",{class:"relative cursor-default select-none py-2 pl-3 pr-9 hover:bg-gray-100",onClick:U=>b(w)},[t("span",re,f(w.code),1)],8,ae)]),_:2},1024))),256))]),_:1})):m("",!0),s.error?(r(),v("div",le,f(s.error),1)):m("",!0)])]),_:3}))}},de={class:"font-semibold text-xl text-gray-800 leading-tight"},ie={key:0},ue={key:1},ce=t("br",null,null,-1),me={key:2},ve=t("br",null,null,-1),pe={class:"flex flex-col"},_e={class:"font-bold"},fe={class:"m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3"},ge={class:"mt-6 flex flex-col"},he={class:"-my-2 -mx-4 sm:-mx-6 lg:-mx-8"},xe={class:"shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5"},ye=["onSubmit"],be={class:"grid grid-cols-1 gap-3 sm:grid-cols-6 pb-2"},ke={class:"sm:col-span-6"},we={class:"sm:col-span-6"},Ve={class:"relative flex items-start"},Ce={class:"flex h-6 items-center"},$e=["disabled"],Se=t("div",{class:"ml-3 text-sm leading-6"},[t("label",{for:"is_customer",class:"font-medium text-gray-900"},"Customer Binding?"),p(" "+f(" ")+" "),t("span",{id:"is_customer",class:"text-gray-500"},[t("span",{class:"sr-only"},"Customer Binding?"),p("retrieve customer data from cms")])],-1),Be={key:0,class:"sm:col-span-6"},Ue=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Customer ",-1),Me={key:1,class:"sm:col-span-6"},Ne={class:"sm:col-span-6"},Oe=t("label",{for:"text",class:"flex justify-start text-sm font-medium text-gray-700"}," Operator ",-1),De={class:"sm:col-span-3"},je={class:"sm:col-span-6"},ze={class:"sm:col-span-6"},Ae={class:"flex space-x-1 mt-5 justify-end"},Ie=t("span",null," Back ",-1),qe={key:0,class:"flex"},Ee={key:1,class:"flex"},Fe=t("span",null," Save ",-1),tt={__name:"Edit",props:{adminCustomerOptions:Object,operatorOptions:Object,vend:Object,type:String},setup(s){const a=s,x=g([]);g([]);const e=g(V(y()));g(!1);const k=g([]),b=g("");C().props.auth.operatorCountry,C().props.auth.operatorRole;const c=C().props.auth.permissions;g(M().format("HH:mm:ss")),A(()=>{a.type=="create"?b.value="Create New":b.value="Edit",x.value=a.adminCustomerOptions.map(o=>({id:o.id,full_name:o.cust_id+" - "+o.company})),k.value=[...a.operatorOptions.data.map(o=>({id:o.id,full_name:o.full_name}))],e.value=a.vend&&a.vend.id?V(a.vend):V(y()),e.value.name=a.vend.name,a.vend.customer_id&&(e.value.is_customer=!0,e.value.customer_id={id:a.vend.person_id,full_name:a.vend.customer_code+" - "+a.vend.customer_name}),a.vend.operator_id&&(e.value.operator_id={id:a.vend.operator_id,full_name:a.vend.operator_name})});function y(){return{code:"",name:"",begin_date:M().format("YYYY-MM-DD"),customer_id:"",operator_id:"",serial_num:"",termination_date:"",private_key:""}}function w(o){e.value.code=o.code}function U(){e.value.clearErrors(),a.type==="create"&&e.value.transform(o=>({...o,customer_id:o.customer_id?o.customer_id.id:null,operator_id:o.operator_id?o.operator_id.id:null})).post("/vends/create",{preserveState:!0,replace:!0}),a.type==="update"&&e.value.transform(o=>({...o,customer_id:o.customer_id?o.customer_id.id:null,operator_id:o.operator_id?o.operator_id.id:null})).post("/vends/"+e.value.id+"/update",{preserveState:!0,replace:!0})}function D(){e.value.post("/settings/"+e.value.id+"/toggle-activation",{})}return(o,l)=>(r(),v(O,null,[i(d(I),{title:"VM Management"}),i(K,null,{header:u(()=>[t("h2",de,[p(f(b.value)+" Vending Machine ",1),s.type=="update"?(r(),v("span",ie,f(s.vend.code),1)):m("",!0),s.vend.customer_name?(r(),v("span",ue,[ce,p(" "+f(s.vend.customer_code)+" - "+f(s.vend.customer_name),1)])):!s.vend.customer_name&&s.vend.name?(r(),v("span",me,[ve,p(" "+f(s.vend.name),1)])):m("",!0)]),t("span",null,[t("div",{class:$(["inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-fit",[s.vend.is_active?"bg-green-200":"bg-red-200"]])},[t("div",pe,[t("span",_e,f(s.vend.is_active?"Active":"Inactive"),1)])],2)])]),default:u(()=>[t("div",fe,[t("div",ge,[t("div",he,[t("div",xe,[t("form",{onSubmit:q(U,["prevent"]),id:"submit"},[t("div",be,[t("div",ke,[s.type=="update"?(r(),h(B,{key:0,modelValue:e.value.code,"onUpdate:modelValue":l[0]||(l[0]=n=>e.value.code=n),error:e.value.errors.code,required:"true",disabled:s.vend.code},{default:u(()=>[p(" Code ")]),_:1},8,["modelValue","error","disabled"])):m("",!0),s.type=="create"?(r(),h(ne,{key:1,modelValue:e.value.code,"onUpdate:modelValue":l[1]||(l[1]=n=>e.value.code=n),onSelected:w,required:"true",error:e.value.errors.code},{default:u(()=>[p(" Code ")]),_:1},8,["modelValue","error"])):m("",!0)]),t("div",we,[t("div",Ve,[t("div",Ce,[E(t("input",{"aria-describedby":"is_customer","onUpdate:modelValue":l[2]||(l[2]=n=>e.value.is_customer=n),disabled:s.vend.customer_id&&s.type=="update",name:"is_customer",type:"checkbox",class:$(["h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600",[s.vend.customer_id&&s.type=="update"?"bg-gray-200 hover:cursor-not-allowed":""]])},null,10,$e),[[F,e.value.is_customer]])]),Se])]),e.value.is_customer?(r(),v("div",Be,[Ue,i(N,{modelValue:e.value.customer_id,"onUpdate:modelValue":l[3]||(l[3]=n=>e.value.customer_id=n),options:x.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])])):m("",!0),e.value.is_customer?m("",!0):(r(),v("div",Me,[i(B,{modelValue:e.value.name,"onUpdate:modelValue":l[4]||(l[4]=n=>e.value.name=n),error:e.value.errors.name},{default:u(()=>[p(" Name ")]),_:1},8,["modelValue","error"])])),t("div",Ne,[Oe,i(N,{modelValue:e.value.operator_id,"onUpdate:modelValue":l[5]||(l[5]=n=>e.value.operator_id=n),options:k.value,trackBy:"id",valueProp:"id",label:"full_name",placeholder:"Select","open-direction":"bottom",class:"mt-1"},null,8,["modelValue","options"])]),t("div",De,[d(c).includes("update vends")?(r(),h(Y,{key:0,modelValue:e.value.begin_date,"onUpdate:modelValue":l[6]||(l[6]=n=>e.value.begin_date=n),error:e.value.errors.begin_date,onInput:l[7]||(l[7]=n=>o.onDateFromChanged())},{default:u(()=>[p(" Begin Date ")]),_:1},8,["modelValue","error"])):m("",!0)]),t("div",je,[i(B,{modelValue:e.value.private_key,"onUpdate:modelValue":l[8]||(l[8]=n=>e.value.private_key=n),error:e.value.errors.private_key,disabled:!d(c).includes("update vends")},{default:u(()=>[p(" Private Key ")]),_:1},8,["modelValue","error","disabled"])])]),t("div",ze,[t("div",Ae,[i(d(H),{href:"/settings"},{default:u(()=>[i(S,{class:"bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"},{default:u(()=>[i(d(Q),{class:"w-4 h-4"}),Ie]),_:1})]),_:1}),d(c).includes("update vends")?(r(),h(S,{key:0,type:"button",class:$(["text-white flex space-x-1",[s.vend.is_active?"bg-yellow-500 hover:bg-yellow-600":"bg-green-500 hover:bg-green-600"]]),onClick:l[9]||(l[9]=n=>D())},{default:u(()=>[s.vend.is_active?m("",!0):(r(),v("span",qe,[i(d(ee),{class:"w-4 h-4 pt-1"}),p("Activate ")])),s.vend.is_active?(r(),v("span",Ee,[i(d(X),{class:"w-4 h-4 pt-1"}),p("Deactivate ")])):m("",!0)]),_:1},8,["class"])):m("",!0),d(c).includes("update vends")?(r(),h(S,{key:1,type:"submit",class:"bg-green-500 hover:bg-green-600 text-white flex space-x-1"},{default:u(()=>[i(d(W),{class:"w-4 h-4"}),Fe]),_:1})):m("",!0)])])],40,ye)])])])])]),_:1})],64))}};export{tt as default};
